using Lextm.SharpSnmpLib;
using Lextm.SharpSnmpLib.Messaging;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Web.Http;
using WebService.Models;

namespace WebService.Controllers
{
    public class SnmpController : ApiController
    {
        private static string InterfaceEnumOid = "1.3.6.1.2.1.2.1.0";

        private static string IngresssOid = "1.3.6.1.2.1.2.2.1.10";
        private static string EgresssOid = "1.3.6.1.2.1.2.2.1.16";
        
        /// <summary>
        /// Retrieves a list of Interfaces for the specified IP and Community String
        /// </summary>
        /// <param name="ip"></param>
        /// <param name="communityString"></param>
        /// <returns></returns>
        public IEnumerable<Interface> GetInterfaces(string ip, string communityString)
        {
            var interfaceQuery = this.GetSnmpValues(ip, communityString, InterfaceEnumOid);
            var interfaceList = new List<Interface>();

            // We don't really need the values from the interface query, just it's length
            // We can then loop through and ask the router for it's interface name and details
            for (int i = 1; i < interfaceQuery.Count() + 2; i++)
			{
                // Query for the interface Id and name
                var interfaceId = this.GetSnmpValues(ip, communityString, "1.3.6.1.2.1.2.2.1.2." + i).FirstOrDefault();
                var interfaceName = this.GetSnmpValues(ip, communityString, "1.3.6.1.2.1.31.1.1.1.18." + i).FirstOrDefault();

                if (interfaceId == null)
                    continue;

                interfaceList.Add(new Interface
                {
                    // The OID is important, as it's appended to our interface through put query in GetThroughput
                    Oid = i, 
                    Id = interfaceId.Data.ToString(),
                    Name = interfaceName.Data.ToString()
                });
			}

            return interfaceList;   
            
        }

        /// <summary>
        /// Retrieves the throughput for the specified interface.
        /// </summary>
        /// <param name="ip"></param>
        /// <param name="communityString"></param>
        /// <param name="interfaceId"></param>
        /// <param name="intervalSec">The router byte count interval. Cisco IOS XE updates every 5 seconds, HP Switches 1 second and Cisco ASR 10 Secs.</param>
        /// <returns></returns>
        public InterfaceThroughput GetThroughput(string ip, string communityString, int interfaceId, int intervalSec)
        {

            var ingress = this.GetSnmpValues(ip, communityString, IngresssOid + "." + interfaceId);
            var egress = this.GetSnmpValues(ip, communityString, EgresssOid + "." + interfaceId);

            // Check we have values for egress and ingress. If either are missing return null.
            if (!(ingress.Any() || egress.Any()))
                return null;
            
            // Our egress/ingress data is a byteCounter rather than throughput.
            // So the number of bytes transferred since the last refresh.
            // We need to convert bytes back into bits then divide by our intervalSec 
            // to get back to bits per second (bps).
            var ingressInt = Int64.Parse(ingress.First().Data.ToString());
            var ingressBps = (ingressInt * 8) / intervalSec;
            var egressInt = Int64.Parse(egress.First().Data.ToString());
            var egressBps = (egressInt * 8) / intervalSec;

            return new InterfaceThroughput()
            {
                Ingress = ingressBps,
                Egress = egressBps,
                Time = DateTime.Now
            };
        }

        /// <summary>
        /// Calls the #SNMP library to grab the specified oid values. Zero sanity checks are on here. 
        /// </summary>
        /// <param name="ip"></param>
        /// <param name="communityString"></param>
        /// <param name="oid"></param>
        /// <returns></returns>
        private IEnumerable<Variable> GetSnmpValues(string ip, string communityString, string oid)
        {
            var result = Messenger.Get(VersionCode.V2,
                           new IPEndPoint(IPAddress.Parse(ip), 161),
                           new OctetString(communityString),
                           new List<Variable> { new Variable(new ObjectIdentifier(oid)) },
                           60000);
            return result.AsEnumerable();
        }
        
    }

}
