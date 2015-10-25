using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;

namespace WebService.Models
{

    public class Interface
    {
        /// <summary>
        /// Interface Oid number for queries
        /// </summary>
        public int Oid { get; set; }
        /// <summary>
        /// Interface Id/Name
        /// </summary>
        public string Id { get; set; }

        /// <summary>
        /// Interface Name/Description
        /// </summary>
        public string Name { get; set; }
    }

}